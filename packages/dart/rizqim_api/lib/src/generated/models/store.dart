// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'store.g.dart';

@JsonSerializable()
class Store {
  const Store({required this.id, required this.name});

  factory Store.fromJson(Map<String, Object?> json) => _$StoreFromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$StoreToJson(this);
}
