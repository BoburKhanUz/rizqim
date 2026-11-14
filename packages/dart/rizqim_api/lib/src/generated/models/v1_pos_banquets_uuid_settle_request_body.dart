// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_banquets_uuid_settle_request_body.g.dart';

@JsonSerializable()
class V1PosBanquetsUuidSettleRequestBody {
  const V1PosBanquetsUuidSettleRequestBody({
    this.shiftUuid,
    this.version,
    this.approvalToken,
  });

  factory V1PosBanquetsUuidSettleRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBanquetsUuidSettleRequestBodyFromJson(json);

  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  final int? version;
  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosBanquetsUuidSettleRequestBodyToJson(this);
}
