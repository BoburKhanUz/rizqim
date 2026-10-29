// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_waitlist_uuid_cancel_request_body.g.dart';

@JsonSerializable()
class V1PosWaitlistUuidCancelRequestBody {
  const V1PosWaitlistUuidCancelRequestBody({this.reason, this.version});

  factory V1PosWaitlistUuidCancelRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosWaitlistUuidCancelRequestBodyFromJson(json);

  final String? reason;
  final int? version;

  Map<String, Object?> toJson() =>
      _$V1PosWaitlistUuidCancelRequestBodyToJson(this);
}
