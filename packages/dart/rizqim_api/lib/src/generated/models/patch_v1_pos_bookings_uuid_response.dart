// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data125.dart';

part 'patch_v1_pos_bookings_uuid_response.g.dart';

@JsonSerializable()
class PatchV1PosBookingsUuidResponse {
  const PatchV1PosBookingsUuidResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PatchV1PosBookingsUuidResponse.fromJson(Map<String, Object?> json) =>
      _$PatchV1PosBookingsUuidResponseFromJson(json);

  final bool success;
  final String message;
  final Data125 data;

  Map<String, Object?> toJson() => _$PatchV1PosBookingsUuidResponseToJson(this);
}
