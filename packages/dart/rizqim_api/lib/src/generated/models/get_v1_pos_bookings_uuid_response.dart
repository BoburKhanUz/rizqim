// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data144.dart';

part 'get_v1_pos_bookings_uuid_response.g.dart';

@JsonSerializable()
class GetV1PosBookingsUuidResponse {
  const GetV1PosBookingsUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBookingsUuidResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1PosBookingsUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data144 data;

  Map<String, Object?> toJson() => _$GetV1PosBookingsUuidResponseToJson(this);
}
