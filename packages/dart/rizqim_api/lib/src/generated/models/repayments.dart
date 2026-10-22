// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'repayments.g.dart';

@JsonSerializable()
class Repayments {
  const Repayments({
    required this.count,
    required this.amount,
    required this.cash,
  });

  factory Repayments.fromJson(Map<String, Object?> json) =>
      _$RepaymentsFromJson(json);

  final int count;
  final String amount;
  final String cash;

  Map<String, Object?> toJson() => _$RepaymentsToJson(this);
}
