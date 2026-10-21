// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'order.dart';

part 'data58.g.dart';

@JsonSerializable()
class Data58 {
  const Data58({
    required this.id,
    required this.type,
    required this.amount,
    required this.balanceBefore,
    required this.balanceAfter,
    required this.order,
    required this.referenceId,
    required this.notes,
    required this.createdAt,
  });

  factory Data58.fromJson(Map<String, Object?> json) => _$Data58FromJson(json);

  final int id;
  final String type;
  final String amount;
  @JsonKey(name: 'balance_before')
  final String balanceBefore;
  @JsonKey(name: 'balance_after')
  final String balanceAfter;
  final Order? order;
  @JsonKey(name: 'reference_id')
  final String? referenceId;
  final String? notes;
  @JsonKey(name: 'created_at')
  final String createdAt;

  Map<String, Object?> toJson() => _$Data58ToJson(this);
}
