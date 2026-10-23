// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sales10.dart';
import 'repayments5.dart';

part 'credit8.g.dart';

@JsonSerializable()
class Credit8 {
  const Credit8({required this.sales, required this.repayments});

  factory Credit8.fromJson(Map<String, Object?> json) =>
      _$Credit8FromJson(json);

  final Sales10 sales;
  final Repayments5 repayments;

  Map<String, Object?> toJson() => _$Credit8ToJson(this);
}
