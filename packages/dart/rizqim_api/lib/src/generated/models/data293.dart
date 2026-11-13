// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'errors.dart';
import 'meta3.dart';

part 'data293.g.dart';

@JsonSerializable()
class Data293 {
  const Data293({required this.errors, required this.meta});

  factory Data293.fromJson(Map<String, Object?> json) =>
      _$Data293FromJson(json);

  final List<Errors> errors;
  final Meta3 meta;

  Map<String, Object?> toJson() => _$Data293ToJson(this);
}
