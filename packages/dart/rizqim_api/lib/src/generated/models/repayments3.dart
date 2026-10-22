// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'repayments3.g.dart';

@JsonSerializable()
class Repayments3 {
  const Repayments3({
    required this.count,
    required this.amount,
    required this.cash,
  });

  factory Repayments3.fromJson(Map<String, Object?> json) =>
      _$Repayments3FromJson(json);

  final int count;
  final String amount;
  final String cash;

  Map<String, Object?> toJson() => _$Repayments3ToJson(this);
}
