<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        // Using dependency injection for settings to improve performance
        $adminRoleId = config('constants.roles.admin');
        $superAdminRoleId = config('constants.roles.super_admin');

        $response = null; // Default response

        $userId = $request->get('user_id');

        if ($userId) {
            // If user_id is provided, fetch the user's jobs
            $response = $this->repository->getUsersJobs($userId);
        } elseif (in_array($request->__authenticatedUser->user_type, [$adminRoleId, $superAdminRoleId])) {
            
            $response = $this->repository->getAll($request);
        }

        return response()->json($response ?: ['message' => 'No data found or access denied']);
    }


    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        // Optionally, validate the ID to ensure it's a valid integer or UUID depending on your system's requirements
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Invalid ID provided'], 400);
        }

        $job = $this->repository->with('translatorJobRel.user')->find($id);

        // Check if the job was found before returning the response
        if (!$job) {
            return response()->json(['message' => 'Job not found'], 404);
        }

        return response()->json($job);
    }


    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        // Validate the incoming request data before handling it
        $validatedData = $request->validate([
            // Add your validation rules here. Example:
            'field_name' => 'required|max:255',
            // Other fields as necessary
        ]);

        // Directly pass the validated data which is safer and cleaner
        $response = $this->repository->store($request->user(), $validatedData);

        // Handle response with proper status codes
        if ($response) {
            return response()->json($response, 201); // 201 Created for successful resource creation
        } else {
            return response()->json(['error' => 'Failed to create resource'], 500); // 500 Internal Server Error if something goes wrong
        }
    }


    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        // Validate the incoming request data before handling it
        $validatedData = $request->validate([
            // Define your validation rules here. Example:
            'field_name' => 'required|string|max:255',
            // Include other fields as necessary
        ]);

        // Use Laravel's built-in authentication to get the authenticated user
        $currentUser = $request->user();

        // Exclude unnecessary fields from the data
        $dataToBeUpdated = Arr::except($validatedData, ['_token', 'submit']);

        // Pass validated and sanitized data to the repository
        $response = $this->repository->updateJob($id, $dataToBeUpdated, $currentUser);

        // Handle response with proper status codes
        if ($response) {
            return response()->json($response, 200); // 200 OK for successful update
        } else {
            return response()->json(['error' => 'Failed to update resource'], 500); // 500 Internal Server Error if something goes wrong
        }
    }


    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $adminSenderEmail = config('app.adminemail');
        $data = $request->all();

        $response = $this->repository->storeJobEmail($data);

        // Check the outcome of the operation and return an appropriate response
        if ($response) {
            return response()->json(['message' => 'Email sent successfully'], 200); // Success response
        } else {
            return response()->json(['error' => 'Failed to send email'], 500); // Error response
        }
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if($user_id = $request->get('user_id')) {

            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response($response);
        }

        // Check if the response is not empty and return it
        if (!$response) {
            return response()->json(['message' => 'No history found for this user'], 404); // Return 404 if no history is found
        }

        return response()->json($response, 200); // Return 200 OK with the job history data
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJob($data, $user);

        // Handle different responses based on whether the job was successfully accepted or not
        if ($response) {
            return response()->json(['message' => 'Job accepted successfully'], 200); // 200 OK for success
        } else {
            // It's good practice to return more specific error handling based on why it failed if possible
            return response()->json(['error' => 'Failed to accept job'], 500); // 500 Internal Server Error as a default failure case
        }
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response($response);

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }

    public function distanceFeed(Request $request)
    {
        // Validation can be streamlined and ensure all necessary fields are present
        $validated = $request->validate([
            'distance' => 'sometimes|string',
            'time' => 'sometimes|string',
            'jobid' => 'required|integer',
            'session_time' => 'sometimes|string',
            'flagged' => 'required|boolean',
            'manually_handled' => 'required|boolean',
            'by_admin' => 'required|boolean',
            'admincomment' => 'sometimes|string',
        ]);

        // Simplify data retrieval using ternary operators or null coalescing
        $distance = $validated['distance'] ?? "";
        $time = $validated['time'] ?? "";
        $jobid = $validated['jobid'];
        $session = $validated['session_time'] ?? "";
        $flagged = $validated['flagged'] ? 'yes' : 'no';
        $manually_handled = $validated['manually_handled'] ? 'yes' : 'no';
        $by_admin = $validated['by_admin'] ? 'yes' : 'no';
        $admincomment = $validated['admincomment'] ?? "";

        if ($admincomment == '' && $flagged == 'yes') {
            return response()->json(["error" => "Please, add comment"], 400); // More appropriate status code for missing input
        }

        // Apply updates where necessary
        if ($distance || $time) {
            Distance::where('job_id', $jobid)->update(['distance' => $distance, 'time' => $time]);
        }

        if ($admincomment || $session || $flagged !== 'no' || $manually_handled !== 'no' || $by_admin !== 'no') {
            Job::where('id', $jobid)->update([
                'admin_comments' => $admincomment,
                'flagged' => $flagged,
                'session_time' => $session,
                'manually_handled' => $manually_handled,
                'by_admin' => $by_admin
            ]);
        }

        return response()->json(['message' => 'Record updated!']);
    }


    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

}
