// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'settlement4.dart';
import 'bill.dart';

part 'data241.g.dart';

@JsonSerializable()
class Data241 {
  const Data241({required this.settlement, required this.bill});

  factory Data241.fromJson(Map<String, Object?> json) =>
      _$Data241FromJson(json);

  final Settlement4 settlement;
  final Bill bill;

  Map<String, Object?> toJson() => _$Data241ToJson(this);
}
