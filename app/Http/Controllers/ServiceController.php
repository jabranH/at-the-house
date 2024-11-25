<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\AgentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Stevebauman\Location\Facades\Location;
use Str;

class ServiceController extends Controller
{
    /**
     * Create a new agent service.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function agentServiceCreate(Request $request)
    {
        $request->validate([
            'service_type'=> 'required',
            'service_name' => 'required',
            'short_description' => 'required',
            'address' => 'required',
            'message_number' => 'required',
            'phone_number' => 'required',
            'category_id' => 'required',
            'hours' => 'required', // Add this line for hours validation
            'featured_image' => 'required|image|mimes:jpeg,png,jpg|max:2048', 
            'banner_image' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = auth()->user();

        if ($user->hasAnyRole(['admin', 'agent'])) {
            if ($user->hasRole('admin')) {
                $userId = $request->input('user_id') ?? $user->id;
                $service = AgentService::create([
                    'service_type' => $request->input('service_type'),
                    'service_name' => $request->input('service_name'),
                    'short_description' => $request->input('short_description'),
                    'address' => $request->input('address'),
                    'message_number' => $request->input('message_number'),
                    'phone_number' => $request->input('phone_number'),
                    'category_id' => $request->input('category_id'),
                    'user_id' => $userId,
                    'hours' => $request->input('hours'), // Add this line for hours
                ]);
            } elseif ($user->hasRole('agent')) {
                $service = AgentService::create([
                    'service_type' => $request->input('service_type'),
                    'service_name' => $request->input('service_name'),
                    'short_description' => $request->input('short_description'),
                    'address' => $request->input('address'),
                    'message_number' => $request->input('message_number'),
                    'phone_number' => $request->input('phone_number'),
                    'category_id' => $request->input('category_id'),
                    'user_id' => $user->id,
                    'hours' => $request->input('hours'), // Add this line for hours
                ]);
            }

            if ($request->hasFile('featured_image')) {
                $featuredImage = $request->file('featured_image');
                $filePath = $featuredImage->store('featured_image', 'public'); // Stores in storage/app/public/featured_images
                //return response()->json(['message' => $filePath], 400);
                $service->featured_image = $filePath;
            } else {
                return response()->json(['message' => 'Featured image upload failed'], 400);
            }

            if ($request->hasFile('banner_image')) {
                $banner_image = $request->file('banner_image');
                $filePaths = $banner_image->store('banner_image', 'public'); // Stores in storage/app/public/featured_images
                $service->banner_image = $filePaths;
            } else {
                return response()->json(['message' => 'Banner image upload failed'], 400);
            }

            // if ($request->hasFile('banner_image')) {
            //     $bannerImage = $request->file('banner_image');
            //     $service->update(['banner_image' => $bannerImage]);
            // }
            $service->save();

            return response()->json(['message' => 'Service created successfully', 'service' => $service], 201);
        } else {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
    }

    /**
     * Get all services for an agent or all services for an admin.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllAgentServices()
    {
        
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($user->hasRole('agent')) {
            $services = AgentService::where('user_id', $user->id)->get();

            foreach ($services as $service) {
                if ($service->featured_image) {
                    $service->featured_image = url('storage/' . $service->featured_image);
                }

                if ($service->banner_image) {
                    $service->banner_image = url('storage/' . $service->banner_image);
                }
            }
            return response()->json(['success' => true, 'services' => $services]);
        }elseif ($user->hasRole('admin')) {
            $allowedCategoryTypes = ['popular', 'most_demanding'];
            $result = [];

            try {
                foreach ($allowedCategoryTypes as $categoryType) {
                // $services = DB::select("SELECT * FROM services WHERE category_type = ?", [$categoryType]);
                $services = DB::table('agent_services')
                    ->join('services', 'agent_services.category_id', '=', 'services.id')
                    ->where('agent_services.service_type', [$categoryType])
                    ->select('agent_services.*', 'services.category_name', 'services.category_type')
                    ->get();
                    $result[$categoryType] = $services;
                }

                foreach ($services as $service) {
                    if ($service->featured_image) {
                        $service->featured_image = url('storage/' . $service->featured_image);
                    }

                    if ($service->banner_image) {
                        $service->banner_image = url('storage/' . $service->banner_image);
                    }
                }

                // Return all services grouped by category type
                return response()->json(['success' => true, 'services' => $result]);

            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => 'An error occurred while retrieving services', 'error' => $e->getMessage()], 500);
            }
        }else{
            return response()->json(['error' => 'Unauthorized'], 403);
        }
    }

    public function getAgentServicesApp(Request $request)
    {
        // $user = Auth::user();
        

        // if ($user->hasRole('agent')) {
        //     $services = AgentService::where('user_id', $user->id)->get();
        // } elseif ($user->hasRole('admin')) {
        //     $services = AgentService::all();
        // } else {
        //     return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        // }

        // return response()->json(['success' => true, 'services' => $services]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        // if ($user->hasRole('agent')) {
        //     $services = AgentService::where('user_id', $user->id)->get();

        //     foreach ($services as $service) {
        //         if ($service->featured_image) {
        //             $service->featured_image = url('storage/' . $service->featured_image);
        //         }

        //         if ($service->banner_image) {
        //             $service->banner_image = url('storage/' . $service->banner_image);
        //         }
        //     }
        //     return response()->json(['success' => true, 'services' => $services]);
        // }elseif ($user->hasRole('admin')) {

        $ip = $request->ip();
        $userGeoCode = $this->getGeoCodeFromIp($ip);

        $allowedCategoryTypes = ['popular', 'most_demanding'];
        $result = [];

        try {
            foreach ($allowedCategoryTypes as $categoryType) {
                $services = DB::table('agent_services')
                    ->join('services', 'agent_services.category_id', '=', 'services.id')
                    ->where('agent_services.service_type', $categoryType) // Fixed the where condition
                    ->select('agent_services.*', 'services.category_name', 'services.category_type', 'services.image')
                    ->get();
                    
                foreach ($services as $service) {
                    if ($service->featured_image && !str_starts_with($service->featured_image, 'http')) {
                        $service->featured_image = url('storage/' . $service->featured_image);
                    }
                    if ($service->banner_image && !str_starts_with($service->banner_image, 'http')) {
                        $service->banner_image = url('storage/' . $service->banner_image);
                    }
                    if ($service->image && !str_starts_with($service->image, 'http')) {
                        $service->image = url('storage/' . $service->image);
                    }
                    
                    try {
                        $serviceGeoCode = $this->getGeoCodeFromAddress("", "", "", "");
                        $distance = $this->calculateDistance($userGeoCode, $serviceGeoCode);
                    } catch (Exception $e) {
                        $distance = "error";
                    }
                    $service->distance = $distance;
                }
                $result[$categoryType] = $services;
            }
        
            // Return all services grouped by category type
            return response()->json(['success' => true, 'services' => $result]);
        
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'An error occurred while retrieving services', 'error' => $e->getMessage()], 500);
        }
        // }else{
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }
    }

    public function getAllAgentsServices()
    {

        $allowedCategoryTypes = ['popular', 'most_demanding'];
        $result = [];

        try {
            foreach ($allowedCategoryTypes as $categoryType) {
               // $services = DB::select("SELECT * FROM services WHERE category_type = ?", [$categoryType]);
               $services = DB::table('agent_services')
                ->join('services', 'agent_services.category_id', '=', 'services.id')
                ->where('agent_services.service_type', [$categoryType])
                ->select('agent_services.*', 'services.category_name', 'services.category_type')
                ->get();
                $result[$categoryType] = $services;
            }

            // Return all services grouped by category type
            return response()->json(['success' => true, 'services' => $result]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'An error occurred while retrieving services', 'error' => $e->getMessage()], 500);
        }
    }

    // public function getAgentServiceApp($id)
    // {
    //     $user = Auth::user();

    //     try { 
    //         $service = AgentService::findOrFail($id);

    //         // // Check user roles if needed, e.g., agents can only access their own services
    //         // if ($user->hasRole('agent') && $service->user_id != $user->id) {
    //         //     return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
    //         // }

    //         // Append URL to the featured image if it exists
    //         if ($service->featured_image) {
    //             $service->featured_image = url('storage/' . $service->featured_image);
    //         }

    //         return response()->json(['success' => true, 'service' => $service]);
    //     } catch (ModelNotFoundException $e) {
    //         return response()->json(['success' => false, 'error' => 'Service not found'], 404);
    //     }
    // }

    public function getAgentServiceApp($id)
    {
        $user = Auth::user();

        try { 
            // Use a join to get additional details from the services table
            $service = DB::table('agent_services')
                        ->join('services', 'agent_services.category_id', '=', 'services.id')
                        ->where('agent_services.id', $id)
                        ->select('agent_services.*', 'services.category_name', 'services.category_type', 'services.image')
                        ->first();

            if (!$service) {
                return response()->json(['success' => false, 'error' => 'Service not found'], 404);
            }

            // Append URL to the featured image if it exists
            if ($service->featured_image) {
                $service->featured_image = url('storage/' . $service->featured_image);
            }

            if ($service->image) {
                $service->image = url('storage/' . $service->image);
            }

            return response()->json(['success' => true, 'service' => $service]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Service not found'], 404);
        }
    }

    /**
     * Get a specific agent service by ID.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAgentService($id)
    {
        $user = Auth::user();

        try { 
            $service = AgentService::findOrFail($id);
            if ($service->featured_image) {
                $service->featured_image = url('storage/' . $service->featured_image);
            }
            if ($service->banner_image) {
                $service->banner_image = url('storage/' . $service->banner_image);
            }
            if ($user->hasRole('agent') && $service->user_id == $user->id) {
                // Agent can retrieve their own service
            } elseif ($user->hasRole('admin') || $user->hasRole('user')) {
                // Admin and User can retrieve any service
            } else {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json(['service' => $service]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Service not found'], 404);
        }
    }

    /**
     * Update a specific agent service by ID.
     *
     * @param  int  $id
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    // public function updateAgentService($id, Request $request)
    // {
    //     $user = Auth::user();

    //     try {
    //         $service = AgentService::findOrFail($id);

    //         $imagePath = $request->hasFile('featuredImage') ? $request->file('featuredImage')->store('featured_image', 'public') : null;

    //         if ($user->hasRole('agent') && $service->user_id == $user->id) {
    //             $service->update($request->all());
    //         } elseif ($user->hasRole('admin')) {
    //             $service->update($request->all());
    //         } else {
    //             return response()->json(['error' => 'Unauthorized'], 403);
    //         }

    //         return response()->json(['message' => 'Service updated successfully', 'service' => $service]);
    //     } catch (ModelNotFoundException $e) {
    //         return response()->json(['error' => 'Service not found'], 404);
    //     }
    // }

    public function updateAgentService($id, Request $request)
    {
        $user = Auth::user();

        try {
            $service = AgentService::findOrFail($id);
        
            // Process `featuredImage`
            if ($request->has('featuredImage')) {
                $featuredImageData = $request->input('featuredImage');
                $featuredImageData = str_replace('data:image/png;base64,', '', $featuredImageData);
                $featuredImageData = str_replace(' ', '+', $featuredImageData);
                $featuredImageName = uniqid() . '_featured.png'; // Ensure unique naming
                Storage::disk('public')->put('featured_image/' . $featuredImageName, base64_decode($featuredImageData));
                $service->featured_image = 'featured_image/' . $featuredImageName;
            }
        
            // Process `bannerImage`
            if ($request->has('bannerImage')) {
                $bannerImageData = $request->input('bannerImage');
                $bannerImageData = str_replace('data:image/png;base64,', '', $bannerImageData);
                $bannerImageData = str_replace(' ', '+', $bannerImageData);
                $bannerImageName = uniqid() . '_banner.png'; // Ensure unique naming
                Storage::disk('public')->put('banner_image/' . $bannerImageName, base64_decode($bannerImageData));
                $service->banner_image = 'banner_image/' . $bannerImageName;
            }
        
            // Save updated images to the database
            $service->save();
        
            // Update other fields based on role
            if ($user->hasRole('agent') && $service->user_id == $user->id) {
                $service->update($request->except(['featuredImage', 'bannerImage']));
            } elseif ($user->hasRole('admin')) {
                $service->update($request->except(['featuredImage', 'bannerImage']));
            } else {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        
            return response()->json(['message' => 'Service updated successfully', 'service' => $service]);
        
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Service not found'], 404);
        }
    }


    /**
     * Delete a specific agent service by ID.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAgentService($id)
    {
        $user = Auth::user();

        try {
            $service = AgentService::findOrFail($id);

            if ($user->hasRole('agent') && $service->user_id == $user->id) {
                $service->delete();
            } elseif ($user->hasRole('admin')) {
                $service->delete();
            } else {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json(['message' => 'Service deleted successfully']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Service not found'], 404);
        }
    }

    private function getGeoCodeFromIp(String $ip) {
        $currentUserInfo = Location::get($ip);

        if ($currentUserInfo) {
            return [
                'latitude' => $currentUserInfo->latitude,
                'longitude' => $currentUserInfo->longitude,
            ];
        }

        // Return null if location data is unavailable
        return null;
    }

    private function getGeoCodeFromAddress(String $street, String $city, String $state, String $zip) {
        $street = '4600+Silver+Hill+Rd';
        $city = 'Washington';
        $state = 'DC';
        $zip = '20233';
        $benchmark = 'Public_AR_Current';
        $format = 'json';

        $url = "https://geocoding.geo.census.gov/geocoder/locations/address?street=" . $street . "&city=" . $city . "&state=" . $state . "&zip=" . $zip . "&benchmark=" . $benchmark . "&format=" . $format;
        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', $url);

        $responseBody = json_decode($response->getBody()->getContents(), true);

        $coordinates = $responseBody['result']['addressMatches'][0]['coordinates'];
        
        return [
            'latitude' => $coordinates['y'],
            'longitude' => $coordinates['x'],
        ];
    }

    private function calculateDistance($userGeoCode, $serviceGeoCode) {
        $userLatitude = $userGeoCode['latitude'];
        $userLongitude = $userGeoCode['longitude'];
        $serviceLatitude = $serviceGeoCode['latitude'];
        $serviceLongitude = $serviceGeoCode['longitude'];

        $earthRadius = 3958.8;
        $dLat = deg2rad($serviceLatitude - $userLatitude);
        $dLon = deg2rad($serviceLongitude - $userLongitude);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($userLatitude)) * cos(deg2rad($serviceLatitude)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = round($earthRadius * $c, 2);

        return $distance;
    }
}
