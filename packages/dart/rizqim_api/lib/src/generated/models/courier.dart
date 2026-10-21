// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'courier.g.dart';

@JsonSerializable()
class Courier {
  const Courier({required this.id, required this.name, required this.phone});

  factory Courier.fromJson(Map<String, Object?> json) =>
      _$CourierFromJson(json);

  final int id;
  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$CourierToJson(this);
}
