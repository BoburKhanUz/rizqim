// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill2.dart';
import 'order5.dart';

part 'data184.g.dart';

@JsonSerializable()
class Data184 {
  const Data184({required this.bill, required this.order});

  factory Data184.fromJson(Map<String, Object?> json) =>
      _$Data184FromJson(json);

  final Bill2 bill;
  final Order5 order;

  Map<String, Object?> toJson() => _$Data184ToJson(this);
}
