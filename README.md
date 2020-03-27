# Acquia API Helper

This class provides a way to access the Acquia v2.0 API. There are 3 functions:
* GET
* POST
* CHECK NOTIFICATION

This provides the functionality required to interact with any Acquia API route. There is also an action class that allows caching and logging with each call. The migrations for this class can be found in the migrations folder, along with that of the User class.

### Features
#### Caching
The get function uses caching. This decreases loading time significantly. The default is set to 5 minutes but this could be extended to have no limit if the user was to be able to invalidate their own caches from the UI.

This works by checking if the identical request has been made recently and then returning the same response from the actions table rather than needing to hit Acquia endpoints.

The caching can be overridden: `$api->get(*URL*, false)`, This is useful for frequently changing endpoints.

You can strip this functionality out if required and it will still function as normal.
#### Notification Links
When submitting a post request the helper will return a link that can later be used to check the status of their job.

Getting notification link:
```php
$notificationLink = $api->post('api/environments/'. $environment . '/databases/' . $database->name .'/backups');
```
Getting status:
 ```php
 $status = $api->checkNotification($notificationLink)
 ```

## Example Usage - Instantiation :
The class takes a User ID Parameter and acts on their behalf.
```php
$api = new APIHelper(Auth::user()->id);
```

## Example Usage - GET:
This provides the blade file with data regarding the user's account details and applications they have access to.
```php
public function index()
{
    $api = new APIHelper(Auth::user()->id);

    return view('home', ['projects' => $api->get('api/applications'), 'account' => $api->get('api/account')]);
}
```

## Example Usage - POST:
This function is used to backup all the databases for a given environment. As the POST function returns the notification link and Acquia acts as a queue, keeping the last notification link allows you to tell when all the submitted jobs have completed.
```php
public function environmentBackup($environment, $api)
{
  $data = $this->retrieve('api/environments/'. $environment.'/databases' ,$api );
  foreach ($data->_embedded->items as $database) {
      $data = $api->post('api/environments/'. $environment . '/databases/' . $database->name .'/backups');
  }

  return json_encode($data);
}
```

## Example Usage - checkNotification:
The API provides a notification link with each post to allow you to check the progress of a request. In my projects I use ajax to call a function similar to the one shown below to retrieve the status of a notification.
```php
public function notification(Request $request)
{
    if (is_null($user = ($this->auth($request->authtoken)))) {
        $response = array(
            'status' => 'error',
            'notification' => json_encode($request->notification),
            'data' => 'null',
        );
        return response()->json($response);
    }

    $api = new APIHelper($user->id);
    $status = $api->checkNotification(json_decode($request->notification));

    $response = array(
        'status' => $status,
        'notification' => json_decode($request->notification),
        'data' => 'Notification request on behalf of: ' . $user->name,
    );
    return response()->json($response);
}
```
