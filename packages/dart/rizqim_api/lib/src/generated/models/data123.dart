// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data123.g.dart';

@JsonSerializable()
class Data123 {
  const Data123({required this.id});

  factory Data123.fromJson(Map<String, Object?> json) =>
      _$Data123FromJson(json);

  final int id;

  Map<String, Object?> toJson() => _$Data123ToJson(this);
}
