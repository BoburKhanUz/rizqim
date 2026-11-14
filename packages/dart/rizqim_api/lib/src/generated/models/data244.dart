// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'warnings21.dart';

part 'data244.g.dart';

@JsonSerializable()
class Data244 {
  const Data244({required this.warnings});

  factory Data244.fromJson(Map<String, Object?> json) =>
      _$Data244FromJson(json);

  final List<Warnings21> warnings;

  Map<String, Object?> toJson() => _$Data244ToJson(this);
}
