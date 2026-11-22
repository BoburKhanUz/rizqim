// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'errors.dart';
import 'meta3.dart';

part 'data352.g.dart';

@JsonSerializable()
class Data352 {
  const Data352({required this.errors, required this.meta});

  factory Data352.fromJson(Map<String, Object?> json) =>
      _$Data352FromJson(json);

  final List<Errors> errors;
  final Meta3 meta;

  Map<String, Object?> toJson() => _$Data352ToJson(this);
}
