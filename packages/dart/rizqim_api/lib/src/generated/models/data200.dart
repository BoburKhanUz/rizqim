// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'errors.dart';
import 'meta2.dart';

part 'data200.g.dart';

@JsonSerializable()
class Data200 {
  const Data200({required this.errors, required this.meta});

  factory Data200.fromJson(Map<String, Object?> json) =>
      _$Data200FromJson(json);

  final List<Errors> errors;
  final Meta2 meta;

  Map<String, Object?> toJson() => _$Data200ToJson(this);
}
