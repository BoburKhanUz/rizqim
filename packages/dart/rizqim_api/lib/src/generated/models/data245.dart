// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'warnings21.dart';

part 'data245.g.dart';

@JsonSerializable()
class Data245 {
  const Data245({required this.warnings});

  factory Data245.fromJson(Map<String, Object?> json) =>
      _$Data245FromJson(json);

  final List<Warnings21> warnings;

  Map<String, Object?> toJson() => _$Data245ToJson(this);
}
