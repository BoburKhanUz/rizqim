// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data101.g.dart';

@JsonSerializable()
class Data101 {
  const Data101({required this.id});

  factory Data101.fromJson(Map<String, Object?> json) =>
      _$Data101FromJson(json);

  final int id;

  Map<String, Object?> toJson() => _$Data101ToJson(this);
}
