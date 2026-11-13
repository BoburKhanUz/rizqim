// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account3.dart';
import 'entry.dart';

part 'data230.g.dart';

@JsonSerializable()
class Data230 {
  const Data230({required this.account, required this.entry});

  factory Data230.fromJson(Map<String, Object?> json) =>
      _$Data230FromJson(json);

  final Account3 account;
  final Entry entry;

  Map<String, Object?> toJson() => _$Data230ToJson(this);
}
