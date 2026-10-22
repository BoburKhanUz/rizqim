// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_bookings_uuid_cancel_request_body.g.dart';

@JsonSerializable()
class V1PosBookingsUuidCancelRequestBody {
  const V1PosBookingsUuidCancelRequestBody({
    required this.reason,
    this.version,
  });

  factory V1PosBookingsUuidCancelRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBookingsUuidCancelRequestBodyFromJson(json);

  final String reason;
  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosBookingsUuidCancelRequestBodyToJson(this);
}
