// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user.dart';
import 'store.dart';

part 'data185.g.dart';

@JsonSerializable()
class Data185 {
  const Data185({
    required this.user,
    required this.store,
    required this.smsVerificationRequired,
  });

  factory Data185.fromJson(Map<String, Object?> json) =>
      _$Data185FromJson(json);

  final User user;
  final Store? store;
  @JsonKey(name: 'sms_verification_required')
  final bool smsVerificationRequired;

  Map<String, Object?> toJson() => _$Data185ToJson(this);
}
