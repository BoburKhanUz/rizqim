// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'shift5.dart';
import 'report3.dart';

part 'data206.g.dart';

@JsonSerializable()
class Data206 {
  const Data206({required this.shift, required this.report});

  factory Data206.fromJson(Map<String, Object?> json) =>
      _$Data206FromJson(json);

  final Shift5 shift;
  final Report3 report;

  Map<String, Object?> toJson() => _$Data206ToJson(this);
}
