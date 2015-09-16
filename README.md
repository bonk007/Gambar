# Gambar

```
"require-dev":{
...
"bonk007/gambar":"dev-master"
...
}
```

```
composer update
```

```
composer install
```
#Save Image
##Laravel
```
Route::get('/', function () {
    // return view('welcome');
    $dir = base_path('public');
    $src = $dir . '/0.jpg';
    Gambar\Gambar::set($src)->save($dir, 'new');
});
```
