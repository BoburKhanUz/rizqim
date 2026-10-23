// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sales2.dart';
import 'repayments.dart';

part 'credit4.g.dart';

@JsonSerializable()
class Credit4 {
  const Credit4({required this.sales, required this.repayments});

  factory Credit4.fromJson(Map<String, Object?> json) =>
      _$Credit4FromJson(json);

  final Sales2 sales;
  final Repayments repayments;

  Map<String, Object?> toJson() => _$Credit4ToJson(this);
}
