// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill3.dart';
import 'order6.dart';

part 'data247.g.dart';

@JsonSerializable()
class Data247 {
  const Data247({required this.bill, required this.order});

  factory Data247.fromJson(Map<String, Object?> json) =>
      _$Data247FromJson(json);

  final Bill3 bill;
  final Order6 order;

  Map<String, Object?> toJson() => _$Data247ToJson(this);
}
