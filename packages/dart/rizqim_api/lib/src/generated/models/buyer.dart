// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'buyer.g.dart';

@JsonSerializable()
class Buyer {
  const Buyer({required this.name, required this.phone});

  factory Buyer.fromJson(Map<String, Object?> json) => _$BuyerFromJson(json);

  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$BuyerToJson(this);
}
