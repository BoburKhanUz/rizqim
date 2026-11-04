// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'errors.dart';
import 'meta2.dart';

part 'data271.g.dart';

@JsonSerializable()
class Data271 {
  const Data271({required this.errors, required this.meta});

  factory Data271.fromJson(Map<String, Object?> json) =>
      _$Data271FromJson(json);

  final List<Errors> errors;
  final Meta2 meta;

  Map<String, Object?> toJson() => _$Data271ToJson(this);
}
