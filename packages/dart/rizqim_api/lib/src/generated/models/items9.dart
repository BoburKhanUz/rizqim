// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'items9.g.dart';

@JsonSerializable()
class Items9 {
  const Items9({
    required this.uuid,
    required this.billUuid,
    required this.orderUuid,
    required this.branchId,
    required this.provider,
    required this.providerLabel,
    required this.amount,
    required this.transactionId,
    required this.reason,
    required this.status,
    required this.createdAt,
    required this.unmatchedAt,
  });

  factory Items9.fromJson(Map<String, Object?> json) => _$Items9FromJson(json);

  final String uuid;
  @JsonKey(name: 'bill_uuid')
  final String billUuid;
  @JsonKey(name: 'order_uuid')
  final String? orderUuid;
  @JsonKey(name: 'branch_id')
  final int branchId;
  final String provider;
  @JsonKey(name: 'provider_label')
  final String providerLabel;
  final String amount;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  final String? reason;
  final String status;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'unmatched_at')
  final String? unmatchedAt;

  Map<String, Object?> toJson() => _$Items9ToJson(this);
}
