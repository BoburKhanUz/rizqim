// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'bill3.dart';
import 'order6.dart';

part 'data141.g.dart';

@JsonSerializable()
class Data141 {
  const Data141({required this.bill, required this.copy, required this.order});

  factory Data141.fromJson(Map<String, Object?> json) =>
      _$Data141FromJson(json);

  final Bill3 bill;
  final bool copy;
  final Order6 order;

  Map<String, Object?> toJson() => _$Data141ToJson(this);
}
