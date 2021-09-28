@extends('layouts.landing.app')

@section('title','Landing Page | '.\App\Models\BusinessSetting::where(['key'=>'business_name'])->first()->value??'Stack Food')

@section('content')

    <main>
        <div class="main-body-div">
            <!-- Top Start -->
            <section class="top-start">
                <div class="container ">
                    <div class="row">
                        <div class="row col-lg-7 top-content">
                            <div>
                                <h3 class="d-flex justify-content-center justify-content-md-start text-center text-md-left">
                                    {{__('messages.header_title_1')}}
                                </h3>
                                <span
                                    class="d-flex justify-content-center justify-content-md-start text-center text-md-left">
                                     {{__('messages.header_title_2')}}
                                </span>
                                <h4 class="d-flex justify-content-center justify-content-md-start text-center text-md-left">
                                    {{__('messages.header_title_3')}}
                                </h4>
                            </div>

                            <div class="download-buttons">
                                <div class="play-store">
                                    <a href="https://play.google.com">
                                        <img src="{{asset('public/assets/landing')}}/image/play_store.png">
                                    </a>
                                </div>

                                <div class="apple-store">
                                    <a href="https://www.apple.com/app-store">
                                        <img src="{{asset('public/assets/landing')}}/image/apple_store.png">
                                    </a>
                                </div>

                                <div class="apple-store">
                                    <a href="#">
                                        <img src="{{asset('public/assets/landing')}}/image/browse.png">
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div
                            class="col-lg-5 d-flex justify-content-center justify-content-md-end text-center text-md-right top-image">
                            <img src="{{asset('public/assets/landing')}}/image/double_screen_image.png">
                        </div>
                    </div>
                </div>
            </section>
            <!-- Top End -->

            <!-- About Us Start -->
            <section class="about-us">
                <div class="container">
                    <div class="row featured-section">
                        <div class="col-12 featured-title-m">
                            <span>About Us</span>
                        </div>
                        <div
                            class="col-lg-6 col-md-6  d-flex justify-content-center justify-content-md-start text-center text-md-left featured-section__image">
                            <img src="{{asset('public/assets/landing')}}/image/about_us_image.png"></img>
                        </div>
                        <!-- <div class="col-lg-3 col-md-0"></div> -->
                        <div class="col-lg-6 col-md-6">
                            <div class="featured-section__content"
                                 class="d-flex justify-content-center justify-content-md-start text-center text-md-left">
                                <span>About Us</span>
                                <h2
                                    class="d-flex justify-content-center justify-content-md-start text-center text-md-left">
                                    {{__('messages.about_title')}}</h2>
                                <p
                                    class="d-flex justify-content-center justify-content-md-start text-center text-md-left">
                                    {!! \Illuminate\Support\Str::limit(\App\CentralLogics\Helpers::get_settings('about_us'),200) !!}
                                </p>
                                <div
                                    class="d-flex justify-content-center justify-content-md-start text-center text-md-left">
                                    <a href="{{route('about-us')}}"
                                       class="btn btn-color-primary text-white rounded align-middle">Read More
                                        ...</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- About Us End -->

            <!-- Why Choose Us Start -->
            <section class="why-choose-us">
                <div class="container">
                    <div class="row choosing-section">
                        <div class="choosing-section__title">
                            <div>
                                <h2>{{__('messages.why_choose_us')}}</h2>
                                <span>{{__('messages.why_choose_us_title')}}</span>
                                <hr class="customed-hr-1">
                            </div>
                        </div>
                        <div class="choosing-section__content">
                            <div>
                                <div class="choosing-section__image-card">
                                    <img src="{{asset('public/assets/landing')}}/image/clean_&_cheap_icon.png"></img>
                                </div>
                                <div style="margin: 0px 55px 30px 54px">
                                    <p>Clean & Cheap Price</p>
                                </div>
                            </div>

                            <div>
                                <div class="choosing-section__image-card">
                                    <img src="{{asset('public/assets/landing')}}/image/best_dishes_icon.png"></img>
                                </div>
                                <div style="margin: 0px 54px 30px 55px">
                                    <p>Best Dishes Near You</p>
                                </div>
                            </div>

                            <div>
                                <div class="choosing-section__image-card">
                                    <img
                                        src="{{asset('public/assets/landing')}}/image/virtual_restaurant_icon.png"></img>
                                </div>
                                <div style="margin: 0px 31px 30px 31px">
                                    <p>Your Own Virtual Restaurant</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
            <!-- Why Choose Us End -->

            <!-- Trusted Customers Starts -->
            <section class="trusted-customers">
                <div class="container">
                    <div class="trusted_customers__title">
                        <span class="trusted-customer mt-4" style="font-size: 33px">{{__('messages.trusted_by_customer')}} & {{__('messages.trusted_by_restaurant')}}</span>
                    </div>

                    <div class="mt-5">
                        <div class="demo">
                            <div class="container">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div id="testimonial-slider" class="owl-carousel">
                                            @foreach(include('public/assets/landing/data/testimonial.php') as $data)
                                                <div class="testimonial">
                                                    <div class="pic">
                                                        <img src="{{$data['image']}}"
                                                             alt="">
                                                    </div>
                                                    <div class="testimonial-content">
                                                        <h3 class="testimonial-title">
                                                            {{$data['name']}}
                                                            <small class="post">{{$data['position']}}</small>
                                                        </h3>
                                                        <p class="description">
                                                           {{$data['detail']}}
                                                        </p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <!-- Trusted Customers Ends -->
        </div>
    </main>

@endsection
