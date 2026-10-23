// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_bookings_uuid_confirm_request_body.g.dart';

@JsonSerializable()
class V1PosBookingsUuidConfirmRequestBody {
  const V1PosBookingsUuidConfirmRequestBody({this.version});

  factory V1PosBookingsUuidConfirmRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBookingsUuidConfirmRequestBodyFromJson(json);

  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosBookingsUuidConfirmRequestBodyToJson(this);
}
