// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'members2.dart';

part 'data288.g.dart';

@JsonSerializable()
class Data288 {
  const Data288({required this.created, required this.members});

  factory Data288.fromJson(Map<String, Object?> json) =>
      _$Data288FromJson(json);

  final int created;
  final List<Members2> members;

  Map<String, Object?> toJson() => _$Data288ToJson(this);
}
