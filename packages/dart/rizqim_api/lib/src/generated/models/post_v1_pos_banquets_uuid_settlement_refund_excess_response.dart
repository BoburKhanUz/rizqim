// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data239.dart';

part 'post_v1_pos_banquets_uuid_settlement_refund_excess_response.g.dart';

@JsonSerializable()
class PostV1PosBanquetsUuidSettlementRefundExcessResponse {
  const PostV1PosBanquetsUuidSettlementRefundExcessResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBanquetsUuidSettlementRefundExcessResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBanquetsUuidSettlementRefundExcessResponseFromJson(json);

  final bool success;
  final String message;
  final Data239 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBanquetsUuidSettlementRefundExcessResponseToJson(this);
}
