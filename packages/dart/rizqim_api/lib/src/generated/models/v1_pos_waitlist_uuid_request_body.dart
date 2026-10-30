// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_waitlist_uuid_request_body.g.dart';

@JsonSerializable()
class V1PosWaitlistUuidRequestBody {
  const V1PosWaitlistUuidRequestBody({
    this.guestName,
    this.guestPhone,
    this.partySize,
    this.quotedWaitMinutes,
    this.note,
    this.version,
  });

  factory V1PosWaitlistUuidRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosWaitlistUuidRequestBodyFromJson(json);

  @JsonKey(name: 'guest_name')
  final String? guestName;
  @JsonKey(name: 'guest_phone')
  final String? guestPhone;
  @JsonKey(name: 'party_size')
  final int? partySize;
  @JsonKey(name: 'quoted_wait_minutes')
  final int? quotedWaitMinutes;
  final String? note;
  final int? version;

  Map<String, Object?> toJson() => _$V1PosWaitlistUuidRequestBodyToJson(this);
}
