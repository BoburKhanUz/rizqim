// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_pos_banquets_uuid_settlement_refund_excess_request_body.g.dart';

@JsonSerializable()
class V1PosBanquetsUuidSettlementRefundExcessRequestBody {
  const V1PosBanquetsUuidSettlementRefundExcessRequestBody({
    this.approvalToken,
  });

  factory V1PosBanquetsUuidSettlementRefundExcessRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1PosBanquetsUuidSettlementRefundExcessRequestBodyFromJson(json);

  @JsonKey(name: 'approval_token')
  final String? approvalToken;

  Map<String, Object?> toJson() =>
      _$V1PosBanquetsUuidSettlementRefundExcessRequestBodyToJson(this);
}
