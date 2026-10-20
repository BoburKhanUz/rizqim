// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/get_v1_me_notification_preferences_response.dart';
import '../models/get_v1_notifications_response.dart';
import '../models/post_v1_notifications_read_response.dart';
import '../models/put_v1_me_notification_preferences_response.dart';
import '../models/v1_me_notification_preferences_request_body.dart';

part 'notifications_api.g.dart';

@RestApi()
abstract class NotificationsApi {
  factory NotificationsApi(Dio dio, {String? baseUrl}) = _NotificationsApi;

  /// GET /me/notification-preferences: the message types that fit the user's role, each with its channels. `available`.
  /// says whether the channel can reach the user at all (Telegram linked, an app with a push token registered).
  @GET('/v1/me/notification-preferences')
  Future<GetV1MeNotificationPreferencesResponse> notificationPreferenceShow();

  /// PUT /me/notification-preferences: `{"preferences": {"order_updates": {"push": false}, "promotions": {"push": true}}}`.
  /// Only what is sent changes. Order updates are on and promotions off until the user says otherwise.
  @PUT('/v1/me/notification-preferences')
  Future<PutV1MeNotificationPreferencesResponse> notificationPreferenceUpdate({
    @Body() required V1MeNotificationPreferencesRequestBody body,
  });

  /// In-app notifications.
  ///
  /// The user's notification feed, newest first, 20 per page, with the unread count.
  ///
  /// [page] - Page number (Laravel pagination).
  @GET('/v1/notifications')
  Future<GetV1NotificationsResponse> orderNotifications({
    @Query('page') int? page = 1,
  });

  /// Mark all notifications read
  @POST('/v1/notifications/read')
  Future<PostV1NotificationsReadResponse> orderMarkNotificationsRead();
}
