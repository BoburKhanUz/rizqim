// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'store3.g.dart';

@JsonSerializable()
class Store3 {
  const Store3({required this.id, required this.name, required this.timezone});

  factory Store3.fromJson(Map<String, Object?> json) => _$Store3FromJson(json);

  final int id;
  final String name;
  final String timezone;

  Map<String, Object?> toJson() => _$Store3ToJson(this);
}
