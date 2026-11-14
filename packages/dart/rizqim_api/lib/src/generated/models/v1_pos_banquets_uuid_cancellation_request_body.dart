// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_banquets_uuid_cancellation_request_body.g.dart';

@JsonSerializable()
class V1PosBanquetsUuidCancellationRequestBody {
  const V1PosBanquetsUuidCancellationRequestBody({
    required this.reason,
    this.retained,
    this.overrideReason,
    this.approvalToken,
  });

  factory V1PosBanquetsUuidCancellationRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBanquetsUuidCancellationRequestBodyFromJson(json);

  final String reason;
  final num? retained;
  @JsonKey(name: 'override_reason')
  final String? overrideReason;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosBanquetsUuidCancellationRequestBodyToJson(this);
}
