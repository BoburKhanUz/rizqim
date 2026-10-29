// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data149.dart';

part 'get_v1_pos_bookings_uuid_confirmation_response.g.dart';

@JsonSerializable()
class GetV1PosBookingsUuidConfirmationResponse {
  const GetV1PosBookingsUuidConfirmationResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1PosBookingsUuidConfirmationResponse.fromJson(
    Map<String, Object?> json,
  ) => _$GetV1PosBookingsUuidConfirmationResponseFromJson(json);

  final bool success;
  final String message;
  final Data149 data;

  Map<String, Object?> toJson() =>
      _$GetV1PosBookingsUuidConfirmationResponseToJson(this);
}
