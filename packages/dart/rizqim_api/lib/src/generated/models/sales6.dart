// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sales6.g.dart';

@JsonSerializable()
class Sales6 {
  const Sales6({required this.count, required this.amount});

  factory Sales6.fromJson(Map<String, Object?> json) => _$Sales6FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Sales6ToJson(this);
}
