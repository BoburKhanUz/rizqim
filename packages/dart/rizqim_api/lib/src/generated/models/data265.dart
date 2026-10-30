// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'errors.dart';
import 'meta2.dart';

part 'data265.g.dart';

@JsonSerializable()
class Data265 {
  const Data265({required this.errors, required this.meta});

  factory Data265.fromJson(Map<String, Object?> json) =>
      _$Data265FromJson(json);

  final List<Errors> errors;
  final Meta2 meta;

  Map<String, Object?> toJson() => _$Data265ToJson(this);
}
