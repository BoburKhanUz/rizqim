// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'errors.dart';
import 'meta3.dart';

part 'data351.g.dart';

@JsonSerializable()
class Data351 {
  const Data351({required this.errors, required this.meta});

  factory Data351.fromJson(Map<String, Object?> json) =>
      _$Data351FromJson(json);

  final List<Errors> errors;
  final Meta3 meta;

  Map<String, Object?> toJson() => _$Data351ToJson(this);
}
