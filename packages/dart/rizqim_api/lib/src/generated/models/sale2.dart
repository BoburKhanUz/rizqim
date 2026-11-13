// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sold_by2.dart';

part 'sale2.g.dart';

@JsonSerializable()
class Sale2 {
  const Sale2({
    required this.tender,
    required this.cardType,
    required this.transactionId,
    required this.reference,
    required this.shiftId,
    required this.soldAt,
    required this.soldBy,
  });

  factory Sale2.fromJson(Map<String, Object?> json) => _$Sale2FromJson(json);

  final String tender;
  @JsonKey(name: 'card_type')
  final String? cardType;
  @JsonKey(name: 'transaction_id')
  final String? transactionId;
  final String? reference;
  @JsonKey(name: 'shift_id')
  final int? shiftId;
  @JsonKey(name: 'sold_at')
  final String? soldAt;
  @JsonKey(name: 'sold_by')
  final SoldBy2? soldBy;

  Map<String, Object?> toJson() => _$Sale2ToJson(this);
}
