// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sales6.dart';
import 'repayments3.dart';

part 'credit6.g.dart';

@JsonSerializable()
class Credit6 {
  const Credit6({required this.sales, required this.repayments});

  factory Credit6.fromJson(Map<String, Object?> json) =>
      _$Credit6FromJson(json);

  final Sales6 sales;
  final Repayments3 repayments;

  Map<String, Object?> toJson() => _$Credit6ToJson(this);
}
