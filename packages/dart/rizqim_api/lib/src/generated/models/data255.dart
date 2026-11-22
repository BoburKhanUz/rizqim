// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill5.dart';
import 'order8.dart';

part 'data255.g.dart';

@JsonSerializable()
class Data255 {
  const Data255({required this.bill, required this.copy, required this.order});

  factory Data255.fromJson(Map<String, Object?> json) =>
      _$Data255FromJson(json);

  final Bill5 bill;
  final bool copy;
  final Order8 order;

  Map<String, Object?> toJson() => _$Data255ToJson(this);
}
