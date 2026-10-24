// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'shift5.dart';
import 'report3.dart';

part 'data177.g.dart';

@JsonSerializable()
class Data177 {
  const Data177({required this.shift, required this.report});

  factory Data177.fromJson(Map<String, Object?> json) =>
      _$Data177FromJson(json);

  final Shift5 shift;
  final Report3 report;

  Map<String, Object?> toJson() => _$Data177ToJson(this);
}
