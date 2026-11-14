// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'errors.dart';
import 'meta3.dart';

part 'data345.g.dart';

@JsonSerializable()
class Data345 {
  const Data345({required this.errors, required this.meta});

  factory Data345.fromJson(Map<String, Object?> json) =>
      _$Data345FromJson(json);

  final List<Errors> errors;
  final Meta3 meta;

  Map<String, Object?> toJson() => _$Data345ToJson(this);
}
