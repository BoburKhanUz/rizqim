// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account2.dart';
import 'entry.dart';

part 'data165.g.dart';

@JsonSerializable()
class Data165 {
  const Data165({required this.account, required this.entry});

  factory Data165.fromJson(Map<String, Object?> json) =>
      _$Data165FromJson(json);

  final Account2 account;
  final Entry entry;

  Map<String, Object?> toJson() => _$Data165ToJson(this);
}
