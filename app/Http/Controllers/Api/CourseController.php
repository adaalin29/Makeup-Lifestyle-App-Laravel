<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Auth;
use App\Order;
use App\OrderPackage;
use App\Category;
use App\FreeCourse;
use App\Course;
use Carbon\Carbon;
use App\Subcategory;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

use App\Package;

class CourseController extends Controller
{

  public function userPackages(Request $request){
    $now = Carbon::now()->format('Y-m-d');
    $ordersPackage = OrderPackage::with(['orders','packages.tutorials'])->whereHas('orders', function (Builder $query) use($request) {
      $query->where('orders.id_user',$request->userId)->where('orders.status','platita');
    })->get();
    $modifiedPackages=[];
    foreach($ordersPackage as $orderPackage){
      $modifiedPackages[$orderPackage->package_id] = [];
    }
    foreach($ordersPackage as $key=>$orderPackage){
      array_push($modifiedPackages[$orderPackage->package_id],$orderPackage->packages);
      if($orderPackage->orders['exp_date']>$now){
        $orderPackage->packages['active'] = true;
        $orderPackage->packages['daysLeft'] = Carbon::parse($orderPackage->orders['exp_date'])->diffInDays($now);
        // if($orderPackage->packages['daysLeft'] == 0){
        //   $orderPackage->packages['daysLeft'] = 'astazi';
        // }
      }
    }
    $packages = [];
    if(count($modifiedPackages)>0){
      foreach($modifiedPackages as $package){
        foreach($package as $key=>$item){
          if(isset($item->active) && $item->active){
            array_push($packages,$item);
            break;
          }else{
            if($key == count($package)-1){
              array_push($packages,$item);
            }
          }
        }
      }
    }
    foreach($packages as $package){
      foreach($package->tutorials as $tutorial){
        $tutorial->images = json_decode($tutorial->images, true);
        $video = json_decode($tutorial->video, true)[0];
        $tutorial->video = $video['download_link'];
      }
    }
    return['packages'=>$packages,'success'=>true];
  }

  public function packages(Request $request){
    $now = Carbon::now()->format('Y-m-d');
    $orders = Order::where('id_user',$request->userId)->where('status','platita')->where('exp_date','>',$now)->with('packages')->get();
    $packagesArray = [];
    foreach($orders as $order){
      foreach($order->packages as $package){
        array_push($packagesArray,$package->id);
      }
    };
    $packagesArray = array_unique($packagesArray);
    $messageTitle = setting('mesaj.mesaj-titlu');
    $messageDescription = setting('mesaj.mesaj-descriere');
    $packagesQuery = Package::with('tutorials')->orderBy('free_package','DESC')->orderBy('created_at','DESC');
    if($request->has('search') && trim($request->get('search'))){
      $packagesQuery = Package::search($request->get('search'))->constrain($packagesQuery);
    }
    $packages = $packagesQuery->get();
    foreach($packages as $key=>$package){
      if(in_array($package->id,$packagesArray)){
        $package->active=true;
      }
      $package->countTutorials = 0;
      if(count($package->tutorials)>0){
        foreach($package->tutorials as $tutorial){
            $tutorial->images = json_decode($tutorial->images, true);
            $video = json_decode($tutorial->video, true)[0];
            $tutorial->video = $video['download_link'];
            $package->countTutorials ++;
        }
      }
      if($package->countTutorials==0){
        $packages->forget($key);
      }
    }
    $packages_active = [];
    $packages_inactive = [];
    if($packages && count($packages)>0){
      foreach($packages as $package){
        if(isset($package->active)){
          array_push($packages_active,$package);
        }else{
          array_push($packages_inactive,$package);
        }
      }
      if(count($packages_active)>0 && count($packages_inactive)>0){
        $packages = array_merge($packages_active,$packages_inactive);
      }
      foreach($packages as &$packageItem){
        if($packageItem->free_package ==1)
        $packageItem->active=true;
      }
    }
    // $packages = array_values($packages->toArray());
    if($messageTitle !=null && $messageDescription !=null){
      return ['packages'=>$packages,'messageTitle'=>$messageTitle,'messageDescription'=>$messageDescription,'success'=>true];
    }
    return ['packages'=>$packages,'success'=>true];
  }

  


  public function categories()
    {
      
      $categories =Category::get();
      return ['categories'=>$categories,'success'=>true];
    }
  public function courses()
  {
      
      // $courses =Course::get();
      // foreach($courses as $course){
      //     if($course->videos)
      //     $course->videos = json_decode($course->videos, true);
      //    if($course->video_images)
      //     $course->video_images = json_decode($course->video_images, true);
      // }
    
      $courses = Subcategory::with('courses')->get();
      
      // return ['courses'=>$courses,'success'=>false];
      return ['courses'=>$courses,'success'=>true];
    }
  public function freeCourse(){
     $freeCourse = FreeCourse::first();
     $user_account = Auth::guard('api')->user();
     $exp_date = Carbon::parse($user_account->exp_date)->format('Y-m-d');
     if($exp_date>=Carbon::now()->format('Y-m-d')){
       if($this->checkFreeCourse($freeCourse)){
         $freeCourse->images = json_decode($freeCourse->images, true);
         $video = json_decode($freeCourse->video, true)[0];
         $freeCourse->video = $video['download_link'];
         return ['freeCourse'=>$freeCourse,'success'=>true];
       }
     }
     return ['freeCourse'=>$freeCourse,'success'=>false];
  } 
  public function freeCourseDetail(){
    
      $freeCourse = FreeCourse::first();
      $freeCourse->images = json_decode($freeCourse->images, true);
      $video = json_decode($freeCourse->video, true)[0];
      $freeCourse->video = $video['download_link'];
    
     return ['freeCourse'=>$freeCourse,'success'=>true];
  }
  public function getSubcategories(){
    $subcategories = Subcategory::with('courses')->get();
    $user = Auth::guard('api')->user();
    $userSubscriptions = Order::where('id_user',$user->id)->where('status','platita')->get();

    $checkExpired = \App\Http\Controllers\Api\UserController::checkDate();
    $checkExpired = $checkExpired['valabil'];
    // return['subcategories'=>$userSubscriptions,'checkExpired'=>$checkExpired];
    if($checkExpired && count($userSubscriptions)==0){
      $exp_date = Carbon::parse($user->exp_date)->subMonth()->format('Y-m-d H:i:s');
      $ArraySubcategories = Subcategory::with(['courses' => function ($query) use ($exp_date) {
          $query->where('created_at', '>=',$exp_date);
      }])->get();
      if($ArraySubcategories){
        foreach($ArraySubcategories as $subcat){
          if($subcat->courses != null && count($subcat->courses) > 0){
            $subcatcourse = $subcat->courses;
            foreach($subcatcourse as $key => &$course){
              $course->images = json_decode($course->images, true);
              $video = json_decode($course->video, true)[0];
              $course->video = $video['download_link'];
            }
            
          }
        }
      }
      return ['subcategories'=>$ArraySubcategories,'user'=>$user,'success'=>true,'exp_date'=>$exp_date];
    }
    
    $ArraySubcategories = [];

    if($subcategories){
      foreach($subcategories as $subcat){
        if($subcat->courses != null && count($subcat->courses) > 0){
          $subcatcourse = $subcat->courses;
          foreach($subcatcourse as $key => &$course){


            if($this->checkCourseExpired($userSubscriptions,$course)){
              $course->images = json_decode($course->images, true);
              $video = json_decode($course->video, true)[0];
              $course->video = $video['download_link'];
            } else{
              unset($subcatcourse[$key]);
            }

          }
          $subcat->courses2 = $subcatcourse->values()->all();
          
        }
        $courseArray = $subcat->toArray();
        if(array_key_exists('courses2', $courseArray)){
          $courseArray['courses'] = $courseArray['courses2'];
          unset($courseArray['courses2']);
        }
        array_push($ArraySubcategories,$courseArray);
      }
    }
    return ['subcategories'=>$ArraySubcategories,'user'=>$user,'success'=>true];
  }
  public function checkCourseExpired($subscriptions, $course){
    foreach($subscriptions as $subscription){
      $start_date = Carbon::parse($subscription->created_at)->format('Y-m-d');
      $end_date = Carbon::parse($subscription->created_at)->addMonth()->format('Y-m-d');
      if($course->created_at >= $start_date && $course->created_at <= $end_date){
        return true;
      }
    }
    return false;
  }
  
//   public function checkCourseExpired($subscriptions, $course){
//     foreach($subscriptions as $subscription){
//       $start_date = $subscription->created_at;
//       $end_date = $subscription->created_at->addMonth();
//       if($course->created_at->timestamp >= $start_date->timestamp && $course->created_at->timestamp <= $end_date->timestamp){
//         return true;
//       }
//     }
//     return false;
//   }
  public function checkFreeCourse($course){
      $today =  Carbon::now()->format('Y-m-d');
      if($today >= $course->start_date && $today<=$course->end_date){
        return true;
      }
    return false;
  }
  
  // check if course was added when user had a actove subscription
  public function courseDetail($id)
  {
    $course =Course::find($id);
    
    if($course->banner_video) {
      $course->banner_video = json_decode($course->banner_video, true);
    }
    
    if($course->videoclip1) {
      $course->videoclip1 = json_decode($course->videoclip1, true);
    }
    
    if($course->poze1) {
      $course->poze1 = json_decode($course->poze1, true);
    }
    return ['course'=>$course,'success'=>true];
  }
}