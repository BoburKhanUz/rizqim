// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'customer.g.dart';

@JsonSerializable()
class Customer {
  const Customer({required this.name, required this.phone});

  factory Customer.fromJson(Map<String, Object?> json) =>
      _$CustomerFromJson(json);

  final String? name;
  final String? phone;

  Map<String, Object?> toJson() => _$CustomerToJson(this);
}
