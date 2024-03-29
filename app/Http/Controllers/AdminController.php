<?php

namespace App\Http\Controllers;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;


// use App\Http\Requests\CreateAgentServiceRequest;
use App\Http\Requests\RegisterUserRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
// use Illuminate\Support\Facades\Gate;
// use App\Models\AgentService;

use App\Models\User;
use App\Models\Role;
use App\Models\Service;

class AdminController extends Controller
{
    public function viewAllUsers()
    {
        // Check if the authenticated user is an admin
        if (auth()->user()->roles->contains('name', 'admin')) {
            // Get all users
            $users = User::all();

            // Return a JSON response
            return response()->json(['users' => $users]);
        }

        // If not an admin, return an error response
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    public function viewAgents()
    {
        // Check if the authenticated user is an admin
        if (auth()->user()->roles->contains('name', 'admin')) {
            // Retrieve only users with the 'agent' role
            $agents = User::whereHas('roles', function ($query) {
                $query->where('name', 'agent');
            })->get();

            return response()->json(['agents' => $agents]);
        }

        // If not an admin, return an error response
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    public function registerAgent(RegisterUserRequest $request)
    {
        // Check if the authenticated user is an admin
        if (Auth::user()->roles->contains('name', 'admin')) {
            // Create a new user with the agent role
            $agent = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => bcrypt($request->password),
            ]);

            // Assign the agent role to the new user
            $agent->roles()->attach(Role::where('name', 'agent')->first());

            // Send email verification notification
            if (!$agent->hasVerifiedEmail()) {
                $agent->sendEmailVerificationNotification();
            }

            return response()->json(['message' => 'Verification Request Sent!']);
        }

        // If not an admin, return an error response
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    

    public function verifyEmail(Request $request)
    {
        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // Check if the user exists
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Check if the user has already verified the email
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        // Verify the user's email
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
            return response()->json(['message' => 'Email verified successfully']);
        }

        return response()->json(['error' => 'Email verification failed'], 500);
    }

    public function delete(User $user)
    {
        // Ensure the logged-in user is an admin
        $admin = auth()->user();
        if (!$admin || !$admin->hasRole('admin')) {
            \Illuminate\Support\Facades\Log::info('Unauthorized access: Not an admin');
            return response()->json(['error' => 'Unauthorized access'], 403);
        }

        // Check if the user is not an admin (allow admins to delete all roles)
        if (!$user->hasRole('admin')) {
            // Determine the role(s) of the user before deletion
            $deletedUserRoles = $user->roles->pluck('name')->toArray();

            // Delete the user
            $user->delete();

            // Log the deletion with the user's role(s)
            \Illuminate\Support\Facades\Log::info("Admin {$admin->id} deleted user {$user->id} with role(s): " . implode(', ', $deletedUserRoles));

            return response()->json(['message' => 'User deleted successfully', 'deleted_user_roles' => $deletedUserRoles]);
        }

        // Admins cannot be deleted this way
        \Illuminate\Support\Facades\Log::info('Unauthorized: Admins cannot be deleted this way');
        return response()->json(['error' => 'You are not allowed to delete this user'], 403);
    }


    public function createService(Request $request)
    {
        // Check if the authenticated user is an admin
        if (Auth::user()->roles->contains('name', 'admin')) {
            // Validate the request data, including the image upload
            $request->validate(Service::$rules);

            // Handle image upload
            $imagePath = $request->hasFile('image') ? $request->file('image')->store('service_images', 'public') : null;

            // Create a new service with the image path
            $service = Service::create([
                'category_name' => $request->input('category_name'),
                'image' => $imagePath,
                'category_type' => $request->input('category_type', 'normal'), // Default to 'normal' if not provided
                // Add other fields as needed
            ]);

            return response()->json(['message' => 'Service created successfully', 'service' => $service]);
        }
  
        // If not an admin, return an error response
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    public function getAllServices($categoryType = null)
    {
        // Define allowed category types
        $allowedCategoryTypes = ['popular', 'most_demanding', 'normal'];

        // Check if the provided category type is valid
        if ($categoryType && in_array($categoryType, $allowedCategoryTypes)) {
            // Retrieve services with the specified category type
            $services = Service::where('category_type', $categoryType)->get();
            return response()->json(['services' => $services]);
        } else {
            // If no or invalid category type is provided, return a message
            return response()->json(['message' => 'Invalid or no category type provided.']);
        }
    }



    public function updateService(Request $request, $serviceId)
    {
        // Check if the authenticated user is an admin
        if (Auth::user()->roles->contains('name', 'admin')) {
            // Validate the request data
            $request->validate([
                'category_name' => 'required|unique:services,category_name,' . $serviceId,
                // Add other validation rules for service update
            ]);

            // Find the service by ID
            $service = Service::find($serviceId);

            if (!$service) {
                return response()->json(['error' => 'Service not found'], 404);
            }

            // Update the service
            $service->update([
                'category_name' => $request->input('category_name'),
                // Update other fields as needed
            ]);

            return response()->json(['message' => 'Service updated successfully', 'service' => $service]);
        }

        // If not an admin, return an error response
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    public function deleteService($serviceId)
    {
        // Check if the authenticated user is an admin
        if (Auth::user()->roles->contains('name', 'admin')) {
            // Find the service by ID
            $service = Service::find($serviceId);

            if (!$service) {
                return response()->json(['error' => 'Service not found'], 404);
            }

            // Delete the service
            $service->delete();

            return response()->json(['message' => 'Service deleted successfully']);
        }

        // If not an admin, return an error response
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    public function viewService($serviceId)
    {
        // Check if the authenticated user is an admin
        if (Auth::user()->roles->contains('name', 'admin')) {
            // Find the service by ID
            $service = Service::find($serviceId);

            if (!$service) {
                return response()->json(['error' => 'Service not found'], 404);
            }

            return response()->json(['service' => $service]);
        }

        // If not an admin, return an error response
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    
} 
