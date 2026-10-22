// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sales8.dart';
import 'repayments4.dart';

part 'credit7.g.dart';

@JsonSerializable()
class Credit7 {
  const Credit7({required this.sales, required this.repayments});

  factory Credit7.fromJson(Map<String, Object?> json) =>
      _$Credit7FromJson(json);

  final Sales8 sales;
  final Repayments4 repayments;

  Map<String, Object?> toJson() => _$Credit7ToJson(this);
}
