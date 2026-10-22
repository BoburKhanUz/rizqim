// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'waiter17.g.dart';

@JsonSerializable()
class Waiter17 {
  const Waiter17({required this.id, required this.name});

  factory Waiter17.fromJson(Map<String, Object?> json) =>
      _$Waiter17FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Waiter17ToJson(this);
}
