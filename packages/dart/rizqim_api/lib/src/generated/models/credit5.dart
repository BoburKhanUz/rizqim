// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sales4.dart';
import 'repayments2.dart';

part 'credit5.g.dart';

@JsonSerializable()
class Credit5 {
  const Credit5({required this.sales, required this.repayments});

  factory Credit5.fromJson(Map<String, Object?> json) =>
      _$Credit5FromJson(json);

  final Sales4 sales;
  final Repayments2 repayments;

  Map<String, Object?> toJson() => _$Credit5ToJson(this);
}
