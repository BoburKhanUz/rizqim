// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'halls.dart';

part 'data136.g.dart';

@JsonSerializable()
class Data136 {
  const Data136({required this.halls});

  factory Data136.fromJson(Map<String, Object?> json) =>
      _$Data136FromJson(json);

  final List<Halls> halls;

  Map<String, Object?> toJson() => _$Data136ToJson(this);
}
