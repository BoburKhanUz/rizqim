// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'buyer6.g.dart';

@JsonSerializable()
class Buyer6 {
  const Buyer6({required this.name, required this.phone});

  factory Buyer6.fromJson(Map<String, Object?> json) => _$Buyer6FromJson(json);

  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$Buyer6ToJson(this);
}
