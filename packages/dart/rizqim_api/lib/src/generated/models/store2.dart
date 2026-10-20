// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'store2.g.dart';

@JsonSerializable()
class Store2 {
  const Store2({required this.id, required this.name});

  factory Store2.fromJson(Map<String, Object?> json) => _$Store2FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Store2ToJson(this);
}
