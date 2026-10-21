// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order3.dart';

part 'data56.g.dart';

@JsonSerializable()
class Data56 {
  const Data56({
    required this.uuid,
    required this.provider,
    required this.providerTransactionId,
    required this.status,
    required this.amount,
    required this.paidAt,
    required this.cancelledAt,
    required this.createdAt,
    required this.order,
  });

  factory Data56.fromJson(Map<String, Object?> json) => _$Data56FromJson(json);

  final String uuid;
  final String provider;
  @JsonKey(name: 'provider_transaction_id')
  final String? providerTransactionId;
  final String status;
  final String amount;
  @JsonKey(name: 'paid_at')
  final String? paidAt;
  @JsonKey(name: 'cancelled_at')
  final String? cancelledAt;
  @JsonKey(name: 'created_at')
  final String createdAt;
  final Order3 order;

  Map<String, Object?> toJson() => _$Data56ToJson(this);
}
