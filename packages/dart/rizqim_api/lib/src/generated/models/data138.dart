// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'accounts.dart';
import 'meta.dart';

part 'data138.g.dart';

@JsonSerializable()
class Data138 {
  const Data138({required this.accounts, required this.meta});

  factory Data138.fromJson(Map<String, Object?> json) =>
      _$Data138FromJson(json);

  final List<Accounts> accounts;
  final Meta meta;

  Map<String, Object?> toJson() => _$Data138ToJson(this);
}
