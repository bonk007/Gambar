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
##Laravel
###Save as New Image
```
Route::get('/', function () {
    // return view('welcome');
    $dir = base_path('public');
    $src = $dir . '/0.jpg';
    Gambar\Gambar::set($src)->save($dir, 'new');
});
```
