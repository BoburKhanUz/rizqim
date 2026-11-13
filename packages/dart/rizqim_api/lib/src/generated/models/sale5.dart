// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sold_by5.dart';

part 'sale5.g.dart';

@JsonSerializable()
class Sale5 {
  const Sale5({
    required this.tender,
    required this.cardType,
    required this.transactionId,
    required this.reference,
    required this.shiftId,
    required this.soldAt,
    required this.soldBy,
  });

  factory Sale5.fromJson(Map<String, Object?> json) => _$Sale5FromJson(json);

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
  final SoldBy5? soldBy;

  Map<String, Object?> toJson() => _$Sale5ToJson(this);
}
