// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data172.dart';

part 'get_v1_pos_bookings_calendar_response.g.dart';

@JsonSerializable()
class GetV1PosBookingsCalendarResponse {
  const GetV1PosBookingsCalendarResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBookingsCalendarResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosBookingsCalendarResponseFromJson(json);

  final bool success;
  final String message;
  final Data172 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosBookingsCalendarResponseToJson(this);
}
