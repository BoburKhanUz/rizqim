// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel6.dart';
import 'items22.dart';

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
    this.queueNumber,
    this.items,
    this.tableLabel,
    this.pickupAt,
  });

  factory V1PosOrdersRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1PosOrdersRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  final Channel6 channel;
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

  /// P6-13: the number the till took from its block (it prints the talon from it)
  @JsonKey(name: 'queue_number')
  final int? queueNumber;
  final List<Items22>? items;

  /// P6-25: the table plate of a counter order carried to the table (module table_service)
  @JsonKey(name: 'table_label')
  final String? tableLabel;

  /// P6-34: a takeaway picked up later (module scheduled_pickup): its lines wait for their kitchen time
  @JsonKey(name: 'pickup_at')
  final DateTime? pickupAt;

  Map<String, Object?> toJson() => _$V1PosOrdersRequestBodyToJson(this);
}
