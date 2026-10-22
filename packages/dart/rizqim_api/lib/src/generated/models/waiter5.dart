// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter5.g.dart';

@JsonSerializable()
class Waiter5 {
  const Waiter5({required this.id, required this.name});

  factory Waiter5.fromJson(Map<String, Object?> json) =>
      _$Waiter5FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter5ToJson(this);
}
