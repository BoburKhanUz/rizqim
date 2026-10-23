// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel4.dart';
import 'items21.dart';

part 'v1_pos_orders_request_body.g.dart';

@JsonSerializable()
class V1PosOrdersRequestBody {
  const V1PosOrdersRequestBody({
    required this.channel,
    this.branchId,
    this.tableId,
    this.guestsCount,
    this.waiterId,
    this.externalId,
    this.note,
    this.send,
    this.items,
  });

  factory V1PosOrdersRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosOrdersRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  final Channel4 channel;
  @JsonKey(name: 'table_id')
  final int? tableId;
  @JsonKey(name: 'guests_count')
  final int? guestsCount;
  @JsonKey(name: 'waiter_id')
  final int? waiterId;
  @JsonKey(name: 'external_id')
  final String? externalId;
  final String? note;
  final bool? send;
  final List<Items21>? items;

  Map<String, Object?> toJson() => _$V1PosOrdersRequestBodyToJson(this);
}
